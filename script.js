// Default dummy data
let pets = [
    { id: 101, name: "Max", species: "Dog", owner: "Rahul Sharma" },
    { id: 102, name: "Luna", species: "Cat", owner: "Priya Desai" }
];

// Display pets in the table
function renderPets() {
    const tableBody = document.getElementById('petTableBody');
    tableBody.innerHTML = '';

    pets.forEach(pet => {
        tableBody.innerHTML += `
            <tr>
                <td>${pet.id}</td>
                <td>${pet.name}</td>
                <td>${pet.species}</td>
                <td>${pet.owner}</td>
            </tr>
        `;
    });
}

// Add new pet when form is submitted
document.getElementById('petForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const name = document.getElementById('petName').value;
    const species = document.getElementById('species').value;
    const owner = document.getElementById('ownerName').value;

    const newPet = {
        id: 100 + pets.length + 1,
        name: name,
        species: species,
        owner: owner
    };

    pets.push(newPet);
    renderPets();

    // Clear form inputs
    this.reset();
});

// Run function when page loads
renderPets();
